import React from 'react';
import { api_list, api_misc } from "./api.js";
import { Button } from 'primereact/button';
import PresenceView from './presenceview';
import ElementView from './elementview';
import GroupedItemView from './views/groupeditemview'

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

    sortItemsByIndex = (items, sorters, idx) => {
        console.log("sorting items by index ",sorters,idx);
        var retval={};
        var sorter=sorters[idx];
        if(!sorter) {
            // sort into a list based on name
            retval = items.sort(function(a,b) {
                if(a.name < b.name) return -1;
                if(a.name > b.name) return 1;
                return 0;
            });
        }
        else {
            var templist={};
            var keylist=[];
            for(var i in items) {        
                var item=items[i];
                var key=item[sorter];
                if(!key) {
                    console.log('key not found, setting to undefined');
                    key='undefined';
                }
                if(!templist[key]) {
                    templist[key]=[];
                    keylist.push(key);
                }
                templist[key].push(item);
            }
            keylist.sort();
            for(var i in keylist) {
                retval[keylist[i]] = templist[keylist[i]];
            }
            console.log("group sorted to ",retval);
        
            // sort each list further
            console.log('sorting deeper');
            var results={};
            for(var j in retval) {
                results[j] = this.sortItemsByIndex(retval[j],sorters,idx+1);
            }
            retval=results;
        }
        return retval;
    }


    show = (item) => {
        var dt=this.state.date;
        api_list(this.abortType,'item',{ pagesize: 0, filter: { type: item.name }, special: "with attributes/with presence "+dt})
            .then((res) => {
                // see if we have a sorter in the templates
                var sortBy=[];
                for(var i in item.attributes) {
                    var attr = item.attributes[i];
                    if(attr.remark && attr.remark.groupBy) {
                        sortBy.push(attr.name);
                    }
                }
                // map all attributes of each item back onto the item
                var items = [];
                var itemsById={};
                for(var i in res.data.list) {
                    var item2=res.data.list[i];
                    if(item2.attributes) {
                        for(var j in item2.attributes) {
                            var attr=item2.attributes[j]
                            item2[attr.name]=attr.value;
                        }
                    }
                    delete item2.attributes;
                    if(item2.presence === 'present' || item2.presence === 'absent') {
                        item2.checked=true;
                    }
                    items.push(item2);
                    itemsById[item2.id] = item2;
                }
                console.log("sorting items ",items);
                items=this.sortItemsByIndex(items, sortBy,0);
                this.setState({template: item, items: items, itemsById: itemsById});
            });        
    }

    showElement = (item) => {
        console.log("setting item to ",item);
        for(var i in this.state.template.attributes) {
            var attr=this.state.template.attributes[i];
            if(!item[attr.name]) {
                if(attr.type == "enum") {
                    item[attr.name] = attr.value.split(' ')[0];
                }
                else {
                    item[attr.name] = attr.value;
                }
            }
        }
        this.setState({item:item});
    }

    saveElement = () => {
        api_misc(this.abortType,"item","save",this.state.item)
            .then((res) => {
                if(res.success && res.data) {
                    this.setState({item:null});
                    this.show(this.state.template);
                }
                else {
                    throw Error("network error");
                }
            })
            .catch(() => {
                alert("Network error, try again");
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
        console.log("retrieving presence for date ",date);
        return api_list(this.abortType,'presence',{ pagesize: 0, filter: { type: this.state.template.name }, special: "full presence "+date})
            .then((res) => {
                console.log("received response");
                if(res && res.data && res.data.list) {
                    console.log("calculating data overview");
                    var allpresence=Object.assign({},this.state.presence_list);
                    for(var i in res.data.list) {
                        var pres = res.data.list[i];
                        console.log("presence record is ",pres);
                        var dt = new Date(pres.created);
                        var key = (dt.getMonth() < 9 ? '0' : '') + (dt.getMonth() + 1) + ' ' + (dt.getDate()<10 ? '0':'') + dt.getDate();
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

    showPresenceList= () => {
        console.log("show presence list view");
        // retrieve a list of presence dates 
        this.retrievePresenceForDate(this.state.date)
            .then((lst) => {
                console.log("setting state");
                var presence = this.mergePresence(Object.assign({},this.state.presence_list,lst));
                this.setState({"show_list":true, presence_list:presence});
            });
    }

    showEntryDialog = () => {

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
              {this.state.templates.map((item,idx) => (
                  <Button key={idx} label={item.name} className="p-button-raised p-button-text fullwidth" onClick={()=>this.show(item)}/>
              ))}
              </div>
            </div>
          </div>);
    }
}
