export function pad(number) {
    if (number < 10) {
        return '0' + number;
    }
    return number;
}

export const find_value_of_attribute = (name,lst) => {
    console.log("find value of attribute",name, lst);
    for(var i in lst) {
        var a=lst[i];
        if(a && a.name == name) {
            return a.value;
        }
    }
    return '';
}

export const attribute_by_name = (attrs) =>{
    console.log("converting attributes to by-name-hash ",attrs);
    var retval={};
    attrs.map((a) => {
        retval[a.name]=a;
    });
    return retval;
}

export const create_attribute_from_template = (item) => {
    var def=item.value;
    if(item.type === 'enum') {
        def=item.value.split(' ')[0];
    }
    else if(item.type == 'int') {
        def=0;
    }
    else if(item.type =='year') {
        def=2000;
    }
    else if(item.type == 'number') {
        def=0.0;
    }
    return {
        id: -1,
        name: item.name,
        type: item.type,
        value: def
    }
}

export const create_attributes_from_template = (lst) => {
    return lst.map((item) => create_attribute_from_template(item));
}

export const format_date = (dt) => {
    if(!dt) return '';
    if(!dt.getDate) dt=new Date(dt);
    return dt.getFullYear() +
        '-' + pad(dt.getMonth() + 1) +
        '-' + pad(dt.getDate());    
}

export const date_to_category = (dt) => {
    if(!dt.getDate) dt=new Date(dt);
    var dt2=new Date();

    var yearold=dt.getFullYear();
    var yearnew = dt2.getFullYear();
    var diff=yearnew-yearold;

    if(dt2.getMonth() > 7) {
        // add 1 if we are 'in the next season'
        diff+=1;
    }

    if(diff >= 80) {
        return "V5" + (diff == 89 ? 'L':'');
    }
    if(diff >= 70) {
        return "V4" + (diff == 79 ? 'L':'');
    }
    if(diff >= 60) {
        return "V3" + (diff == 69 ? 'L':'');
    }
    if(diff >= 50) {
        return "V2" + (diff == 59 ? 'L':'');
    }
    if(diff >= 40) {
        return "V1" + (diff == 49 ? 'L':'');
    }
    if(diff < 11) {
        var stage='';
        if(diff==10) stage='L'
        return "K" + stage;
    }
    if(diff < 13) {
        var stage=diff - 10;
        if(stage==2) stage='L'
        return "B" + stage;
    }
    if(diff < 15) {
        var stage=diff - 12;
        if(stage==2) stage='L'
        return "P" + stage;
    }
    if(diff < 18) {
        var stage=diff - 14;
        if(stage==3) stage='L'
        return "C" + stage;
    }
    if(diff < 21) {
        var stage=diff - 17;
        if(stage==3) stage='L'
        return "J" + stage;
    }
    return 'S' + (diff >=30 ? 'F':''); 
}