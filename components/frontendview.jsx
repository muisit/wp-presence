import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import PresenceView from './presenceview';
import ElementView from './elementview';
import GroupedItemView from './views/groupeditemview'
import { pad, date_to_category } from "./functions";

export default class FrontendView extends React.Component {
    constructor(props, context) {
        super(props, context);
        var dt=new Date();
        dt = dt.getFullYear() + "-" + (1+dt.getMonth()) + "-" + dt.getDate();
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
        //console.log("sorting items by index ",sorters,idx);
        var retval={};
        var sorter=sorters[idx];
        // if the index is passed the list of sorters, we sort the remaining items
        // by name and return the result list
        if(!sorter) {
            // sort into a list based on name
            retval = items.sort(function(a,b) {
                var a1=itemsById[a];
                var b1=itemsById[b];
                if(a1.name < b1.name) return -1;
                if(a1.name > b1.name) return 1;
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


    show = (templ) => {
        var dt=this.state.date;
        api_list(this.abortType, 'item', { pagesize: 0, filter: { type: templ.name }, special: "with attributes/with presence "+dt})
            .then((res) => {
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
                for(var i in res.data.list) {
                    var item={
                        original: res.data.list[i],
                        data: {},
                        id: res.data.list[i].id
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

                items=this.sortItemsByIndex(items, sortBy,0, itemsById);
                this.setState({template: templ, items: items, itemsById: itemsById});
            });        
    }

    showElement = (selitem) => {
        var item = this.state.itemsById[selitem.id];
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
        return api_list(this.abortType,'presence',{ pagesize: 0, filter: { type: this.state.template.name }, special: "full presence "+date})
            .then((res) => {
                //console.log("received response");
                if(res && res.data && res.data.list) {
                    //console.log("calculating data overview");
                    var allpresence=Object.assign({},this.state.presence_list);
                    for(var i in res.data.list) {
                        var pres = res.data.list[i];
                        //console.log("presence record is ",pres);
                        var dt = new Date(pres.created);
                        var key = pad(dt.getMonth() + 1) + ' ' + pad(dt.getDate());
                        //console.log("key is ",key);
                        if(!allpresence[key]) {
                            allpresence[key]=[];
                        }
                        allpresence[key] = this.addIfNotPresent(pres,key,allpresence[key]);
                    }
                    //console.log("allpresence of this bracket is ",allpresence);
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

    showPresenceList= () => {
        //console.log("show presence list view");
        // retrieve a list of presence dates 
        this.retrievePresenceForDate(this.state.date)
            .then((lst) => {
                //console.log("setting state");
                var presence = this.mergePresence(Object.assign({},this.state.presence_list,lst));
                this.setState({"show_list":true, presence_list:presence});
            });
    }

    changeDate = (e) => {
        var dt=new Date(e.target.value);
        dt = dt.getFullYear() + "-" + (1+dt.getMonth()) + "-" + dt.getDate();
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
                      <Button label="Terug" className="p-button-raised p-button-text fullwidth" onClick={this.saveElement} />
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
