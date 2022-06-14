import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import PresenceView from './presenceview';
import ElementView from './elementview';
import GroupedItemView from './views/groupeditemview'
import { pad, date_to_category, format_date } from "./functions";
import moment from 'moment';

export default class FrontendView extends React.Component {
    constructor(props, context) {
        super(props, context);
        var dt=moment().format("YYYY-MM-DD");
        this.state = {
            templates: [],
            template: null,
            items: null,
            itemsById: null,
            date: dt,
            item: null,
            show_list: false,
            presence_list: {},
        };
        this.abortType='frontend';
    }
    componentDidMount = () => {
        api_list(this.abortType,'item',{ pagesize: 0, filter: { type:'template' }, special: "with attributes"})
            .then((res) => {
                if(res.data.list) {
                    this.setState({templates: res.data.list});
                }
            });
    }

    sortItemsByIndex = (items, sorters, idx, itemsById) => {
        console.log("sorting items by index ",sorters,idx,itemsById);
        var retval={};
        var sorter=sorters[idx];
        // if the index is passed the list of sorters, we sort the remaining items
        // by name and return the result list
        if(!sorter) {
            // sort into a list based on name
            retval = items.sort(function(a,b) {
                var a1=itemsById[a].original.name.toUpperCase();
                var b1=itemsById[b].original.name.toUpperCase();
                if(a1 < b1) return -1;
                if(a1 > b1) return 1;
                return 0;
            });
        }
        else {
            // store each possible attribute value for this
            // sorter in a keylist, that we can sort later on
            //
            // for each attribute value of this sorter, store
            // the items with that value in the templist
            var templist={};
            var keylist=[];
            for(var i in items) { 
                var id=items[i];       
                var item=itemsById[id];
                if(item) {
                    var key=item.data[sorter];
                    if(!key) {
                        //console.log('key not found, setting to undefined');
                        // no attribute found, make sure we sort all such items
                        // together as 'undefined'
                        key='undefined';
                    }
                    // if the sorting value is not known yet, add it to the list of keys
                    if(!templist[key]) {
                        templist[key]=[];
                        keylist.push(key);
                    }
                    // store the item in the list of items with the same attribute value
                    templist[key].push(item.id);
                }
                else {
                    console.log("missing item for id ",i);
                }
            }
            // sort by attribute value
            keylist.sort();
            // create a list of lists for this sorting attribute
            for(var i in keylist) {
                retval[keylist[i]] = templist[keylist[i]];
            }
            //console.log("group sorted to ",retval);
        
            // sort each list further
            //console.log('sorting deeper');
            // the results need to be sorted further, bucket-per-bucket
            // each bucket has the sorting-attribute-value as key
            var results={};
            for(var j in retval) {
                results[j] = this.sortItemsByIndex(retval[j],sorters,idx+1, itemsById);
            }
            retval=results;
        }
        return retval;
    }

    addElementsToList = (lst,templ,mergewith) => {
        // see if we have a sorter in the templates
        // also look for computed fields BYear and Category
        console.log("parsing list result of template ", templ);
        var sortBy=[];
        var compfields={};
        for (var i in templ.attributes) {
            var attr = templ.attributes[i];
            if(attr.remark && attr.remark.groupBy) {
                sortBy.push(attr.name);
            }
            if(attr.type == 'byear' || attr.type == 'category') {
                console.log("pushing computed field ",attr);
                if(!compfields[attr.value]) {
                    compfields[attr.value]=[];
                }
                compfields[attr.value].push(attr);
            }
        }
        // map all attributes of each item back onto the item
        var items = [];
        var itemsById={};
        for(var i in lst) {
            var item={
                original: lst[i],
                data: {},
                id: lst[i].id
            };

            if (item.original.attributes) {
                for (var j in item.original.attributes) {
                    var attr = item.original.attributes[j]
                    item.data[attr.name]=attr.value;
                }
                // we do not need the original list of attributes, so clean up some memory
                delete item.original.attributes;
            }

            // calculate computed fields
            var keys=Object.keys(compfields);
            if(keys.length) {
                keys.map((key) => {
                    var fields = compfields[key];
                    var value = item.data[key];                            
                    console.log("computed field ",fields,value);
                    if(value) {
                        fields.map((field) => {
                            if (field.type == 'byear') {
                                var dt = new Date(value);
                                console.log("computed field byear says ", dt.getFullYear());
                                item.data[field.name] = dt.getFullYear();
                            }
                            else if (field.type == 'category') {
                                item.data[field.name] = date_to_category(value);
                            }
                        });
                    }
                });
            }

            if (item.original.presence === 'present' || item.original.presence === 'absent') {
                item.data.checked=true;
            }
            console.log("pushing item ",item);
            items.push(item.id);
            itemsById[item.original.id] = item;
        }

        if(mergewith) {
            var keys=Object.keys(mergewith);
            keys.map((key) => {
                items.push(key);
                itemsById[key]=mergewith[key];
            });
            console.log("items is ",items);
            console.log("items by id is ",itemsById);
        }

        items=this.sortItemsByIndex(items, sortBy,0, itemsById);
        this.setState({template: templ, items: items, itemsById: itemsById});
    }

    show = (templ) => {
        var dt=this.state.date;
        api_list(this.abortType, 'item', { pagesize: 0, filter: { type: templ.name, template: templ.id }, special: "with attributes/with presence "+dt})
            .then((res) => {
                this.addElementsToList(res.data.list,templ);
            });        
    }

    showElement = (selitem) => {
        var item = this.state.itemsById[selitem.id];
        if(!item) item=selitem; // if we have a new item, keep the original object
        // set the default values for an item based on its template, if it was not set yet
        for(var i in this.state.template.attributes) {
            var attr=this.state.template.attributes[i];
            if(!item.data[attr.name]) {
                if(attr.type == "enum") {
                    item.data[attr.name] = attr.value.split(' ')[0];
                }
                else {
                    item.data[attr.name] = attr.value;
                }
            }
        }
        if(selitem.changed) item.changed=true; // copy the attribute-has-changed setting
        this.setState({item:item});
    }

    saveElement = () => {
        // convert the attributes back into a list
        var attributes=[];
        this.state.template.attributes.map((a) => {
            if(this.state.item.data[a.name]) {
                attributes.push({name: a.name, value:this.state.item.data[a.name]});
            }
        });
        var item = this.state.item.original;
        item.attributes=attributes;
        api_misc(this.abortType,"item","save",item)
            .then((res) => {
                if(res.success && res.data) {
                    console.log("item saved, clearing element to reset view");
                    this.setState({item:null});
                    console.log("item saved, reloading template");
                    this.show(this.state.template);
                }
                else {
                    console.log("throwing error because of " ,res);
                    throw Error("network error");
                }
            })
            .catch((e) => {
                console.log("caught ",e);
                alert("Network error, try again");
                this.setState({item: null});
            });
    }

    softdelElement = () => {
        // remove the display property if set: it is used in the list overview to gather values
        // we need to display, but we cannot save it
        api_misc(this.abortType, "item", "softdelete", {id: this.state.item.original.id})
            .then((res) => {
                if (res.success && res.data) {
                    this.setState({ item: null});
                    this.show(this.state.template);
                }
                else {
                    throw Error("network error");
                }
            })
            .catch((e) => {
                console.log(e);
                alert("Network error, try again");
                this.setState({ item: null });
            });

    }

    addIfNotPresent = (pres,key, lst) => {
        for(var i in lst) {
            if(lst[i].item == pres.item_id) return lst;
        }
        lst.push({item: pres.item_id, state:pres.state});
        return lst;
    }

    retrievePresenceForDate = (date) => {
        //console.log("retrieving presence for date ",date);
        var range = this.state.template.config && this.state.template.config.range ? parseInt(this.state.template.config.range) : 1;
        return api_list(this.abortType,'presence',{ pagesize: 0, filter: { type: this.state.template.name }, special: "full presence "+date})
            .then((res) => {
                //console.log("received response");
                if(res && res.data && res.data.list) {
                    //console.log("calculating data overview");
                    var allpresence=Object.assign({},this.state.presence_list);
                    for(var i in res.data.list) {
                        var pres = res.data.list[i];
                        //console.log("presence record is ",pres);
                        var key = moment(pres.created).format("MM DD");
                        if(range > 360) key = moment(pres.created).format("YYYY");
                        else if(range > 180) {
                            key = parseInt(moment(pres.created).format("MM"));
                            if(key < 7) key="S1";
                            else key="S2";
                        }
                        else if(range > 90) {
                            key = parseInt(moment(pres.created).format("MM"));
                            if(key < 4) key="Q1";
                            else if(key < 7) key="Q2";
                            else if(key < 10) key="Q3";
                            else key="Q4";
                        }
                        else if(range > 30) key=moment(pres.created).format("MM");

                        //var key = pad(dt.getMonth() + 1) + ' ' + pad(dt.getDate());
                        
                        console.log("key is ",key);
                        if(!allpresence[key]) {
                            allpresence[key]=[];
                        }
                        allpresence[key] = this.addIfNotPresent(pres,key,allpresence[key]);
                    }
                    console.log("allpresence of this bracket is ",allpresence);
                    return allpresence;
                }
                return this.state.presence_list;
            });
    }

    mergePresence = (lst) => {
        var keys = Object.keys(lst);
        keys.sort();
        var retval={};
        for(var i in keys) {
            retval[keys[i]] = lst[keys[i]];
        }
        return retval;
    }

    findMissingPeople = (presence,cb) => {
        var allids={};
        var keys = Object.keys(presence);
        keys.map((itm) => {
            var bracket=presence[itm];
            bracket.map((b)=> {
                console.log("bracket is ",b);
                if(!this.state.itemsById[b.item]) {
                    allids[b.item]=true;
                }
    
            });
        });
        console.log("missing ",Object.keys(allids));
        var ids=Object.keys(allids);
        if(ids.length>0) {
            api_list(this.abortType, 'item', { pagesize: 0, filter: { all: true, type: this.state.template.name, template: this.state.template.id, ids: ids }, special: "with attributes"})
                .then((res) => {
                    console.log("adding elements to list, merging with ",this.state.itemsById);
                    this.addElementsToList(res.data.list,this.state.template,this.state.itemsById);
                    cb();
                });
        }
        else {
            cb();
        }
    }

    showPresenceList= () => {
        //console.log("show presence list view");
        // retrieve a list of presence dates 
        this.retrievePresenceForDate(this.state.date)
            .then((lst) => {
                //console.log("setting state");
                var presence = this.mergePresence(Object.assign({},this.state.presence_list,lst));
                this.findMissingPeople(presence, ()=> this.setState({"show_list":true, presence_list:presence}));
            });
    }

    changeDate = (e) => {
        var dt=moment(e.target.value).format("YYYY-MM-DD");
        this.setState({'date': dt},
            function() { this.show(this.state.template);}
        );
    }
    
    onChangePresence = (itemsById) => {
        this.setState({itemsById: itemsById, presence_list: null});
    }

    addToList = (item) => {
        // add the item to the list, causing a re-render
        this.show(this.state.template);
    }

    render() {
        if(this.state.item) {
            return (
                <div className='container'>
                  <div className='calendar row'>
                    <div className='col-12 offset-md-3 col-md-6'>
                      <Button label={this.state.item.changed ? "Opslaan" : "Terug"} className="p-button-raised p-button-text fullwidth" onClick={this.saveElement} />
                      {!this.state.item.original.deleted && (
                      <Button label="Verwijder" className="p-button-raised p-button-text fullwidth" onClick={this.softdelElement} />
                      )}
                      <ElementView item={this.state.item} template={this.state.template} onChange={this.showElement} />
                    </div>
                  </div>
                </div>
            );
        }
        else if(this.state.show_list) {
            return (
                <div className='container'>
                  <div className='calendar row'>
                    <div className='col-12 offset-md-3 col-md-6'>
                      <Button label="Terug" className="p-button-raised p-button-text fullwidth" onClick={()=>this.setState({show_list:false})} />
                      <PresenceView list={this.state.presence_list} template={this.state.template} date={this.state.date} group={this.state.items} byId={this.state.itemsById} idx='' />
                    </div>
                  </div>
                </div>
            );
        }
        else if(this.state.template && this.state.items) {
            return (<GroupedItemView 
                onBack={() => this.setState({ template: null, items: [], itemsById: {} })} 
                onList={this.showPresenceList} 
                date={this.state.date} 
                onChangeDate={this.changeDate}
                onChangePresence={this.onChangePresence}
                onNewElement={this.addToList}
                onShowElement={this.showElement}
                template={this.state.template}
                group={this.state.items} 
                byId={this.state.itemsById}
                />);
        }
        return (
            <div className='container'>
              <div className='buttons row'>
                <div className='col-12 offset-md-3 col-md-6'>
              {this.state.templates.map((templ,idx) => (
                  <Button key={idx} label={templ.name} className="p-button-raised p-button-text fullwidth" onClick={() => this.show(templ)}/>
              ))}
              </div>
            </div>
          </div>);
    }
}
